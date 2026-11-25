import 'dart:convert';

import 'package:camera/camera.dart';
import 'package:flutter/material.dart';
import 'package:google_sign_in/google_sign_in.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import 'config.dart';
import 'login_screen.dart';

class CamaraScreen extends StatefulWidget {
  const CamaraScreen({super.key});

  @override
  State<CamaraScreen> createState() => _CamaraScreenState();
}

class _CamaraScreenState extends State<CamaraScreen> {
  CameraController? _controller;
  bool _camaraLista = false;
  bool _enviando = false;
  String _nombreOperador = 'Operador';
  Map<String, dynamic>? _ultimoRegistro;

  @override
  void initState() {
    super.initState();
    _cargarDatosYCamara();
  }

  Future<void> _cargarDatosYCamara() async {
    final prefs = await SharedPreferences.getInstance();
    if (mounted) {
      setState(() {
        _nombreOperador =
            prefs.getString('usuario_nombre') ?? 'Operador';
      });
    }

    final camaras = await availableCameras();
    if (camaras.isEmpty) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('No se encontró una cámara disponible')),
        );
      }
      return;
    }

    final trasera = camaras.firstWhere(
      (camara) => camara.lensDirection == CameraLensDirection.back,
      orElse: () => camaras.first,
    );

    _controller = CameraController(
      trasera,
      ResolutionPreset.medium,
      enableAudio: false,
    );
    await _controller!.initialize();

    if (mounted) {
      setState(() => _camaraLista = true);
    }
  }

  Future<void> _capturarYProcesar() async {
    if (_controller == null ||
        !_controller!.value.isInitialized ||
        _enviando) {
      return;
    }

    setState(() => _enviando = true);

    try {
      final foto = await _controller!.takePicture();
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('auth_token') ?? '';

      if (token.isEmpty) {
        throw Exception('No hay una sesión Sanctum activa');
      }

      final request = http.MultipartRequest(
        'POST',
        Uri.parse('${AppConfig.baseUrl}/api/procesar'),
      );
      request.headers['Authorization'] = 'Bearer $token';
      request.headers['Accept'] = 'application/json';
      request.files.add(
        await http.MultipartFile.fromPath('imagen', foto.path),
      );

      final streamedResponse = await request.send();
      final response = await http.Response.fromStream(streamedResponse);

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body) as Map<String, dynamic>;
        setState(() {
          _ultimoRegistro = data['registro'] as Map<String, dynamic>?;
        });
      } else {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error del servidor: ${response.statusCode}'),
          ),
        );
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error al enviar imagen: $e')),
      );
    } finally {
      if (mounted) {
        setState(() => _enviando = false);
      }
    }
  }

  Future<void> _cerrarSesion() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.clear();
    await GoogleSignIn.instance.signOut();

    if (!mounted) return;
    Navigator.pushReplacement(
      context,
      MaterialPageRoute(builder: (_) => const LoginScreen()),
    );
  }

  @override
  void dispose() {
    _controller?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final registro = _ultimoRegistro;

    return Scaffold(
      backgroundColor: const Color(0xFF0F172A),
      appBar: AppBar(
        backgroundColor: const Color(0xFF1E293B),
        foregroundColor: Colors.white,
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Escáner de Peaje', style: TextStyle(fontSize: 18)),
            Text(
              _nombreOperador,
              style: const TextStyle(fontSize: 12, color: Colors.white70),
            ),
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.logout),
            tooltip: 'Cerrar sesión',
            onPressed: _cerrarSesion,
          ),
        ],
      ),
      body: Column(
        children: [
          Expanded(
            flex: 3,
            child: Container(
              margin: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.black,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: Colors.blueGrey.shade700),
              ),
              clipBehavior: Clip.antiAlias,
              child: _camaraLista && _controller != null
                  ? CameraPreview(_controller!)
                  : const Center(child: CircularProgressIndicator()),
            ),
          ),
          if (registro != null)
            Container(
              margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: const Color(0xFF1E293B),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(
                  color: Colors.blueAccent.withValues(alpha: 0.5),
                ),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Placa: ${registro['placa_detectada'] ?? 'No legible'}',
                          style: const TextStyle(
                            color: Colors.amber,
                            fontWeight: FontWeight.bold,
                            fontSize: 16,
                          ),
                        ),
                        Text(
                          'Clase: ${registro['clase_vehiculo']} • Estado: ${registro['estado']}',
                          style: const TextStyle(
                            color: Colors.white70,
                            fontSize: 13,
                          ),
                        ),
                      ],
                    ),
                  ),
                  Text(
                    'Bs. ${registro['tarifa']?['monto'] ?? '0.00'}',
                    style: const TextStyle(
                      color: Colors.greenAccent,
                      fontWeight: FontWeight.bold,
                      fontSize: 18,
                    ),
                  ),
                ],
              ),
            ),
          Padding(
            padding: const EdgeInsets.all(20),
            child: SizedBox(
              width: double.infinity,
              height: 56,
              child: ElevatedButton.icon(
                onPressed: _enviando ? null : _capturarYProcesar,
                icon: _enviando
                    ? const SizedBox(
                        width: 22,
                        height: 22,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : const Icon(Icons.camera_alt, size: 26),
                label: Text(
                  _enviando
                      ? 'Enviando captura...'
                      : 'Capturar y Registrar Vehículo',
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF10B981),
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(14),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

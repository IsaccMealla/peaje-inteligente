import 'dart:async';
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
  final TextEditingController _placaController = TextEditingController();
  Timer? _timerCaseta;
  bool _camaraLista = false;
  bool _casetaActiva = false;
  bool _enviando = false;
  bool _consultando = false;
  String _nombreOperador = 'Operador';
  String? _errorCamara;
  Map<String, dynamic>? _ultimoRegistro;
  Map<String, dynamic>? _respuestaConsulta;

  @override
  void initState() {
    super.initState();
    _cargarDatosYCamara();
  }

  Future<void> _cargarDatosYCamara() async {
    final prefs = await SharedPreferences.getInstance();
    if (mounted) {
      setState(() {
        _nombreOperador = prefs.getString('usuario_nombre') ?? 'Operador';
      });
    }

    try {
      final camaras = await availableCameras();
      if (camaras.isEmpty) {
        throw Exception('No se encontró una cámara disponible');
      }

      final trasera = camaras.firstWhere(
        (camara) => camara.lensDirection == CameraLensDirection.back,
        orElse: () => camaras.first,
      );
      final controller = CameraController(
        trasera,
        ResolutionPreset.medium,
        enableAudio: false,
      );
      await controller.initialize();
      if (!mounted) {
        await controller.dispose();
        return;
      }
      setState(() {
        _controller = controller;
        _camaraLista = true;
      });
    } catch (error) {
      if (mounted) setState(() => _errorCamara = error.toString());
    }
  }

  Future<String> _token() async {
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString('auth_token') ?? '';
    if (token.isEmpty) throw Exception('No hay una sesión Sanctum activa');
    return token;
  }

  Future<Map<String, dynamic>> _enviarFoto(
    XFile foto, {
    required String endpoint,
  }) async {
    final request = http.MultipartRequest(
      'POST',
      Uri.parse('${AppConfig.baseUrl}$endpoint'),
    );
    request.headers['Authorization'] = 'Bearer ${await _token()}';
    request.headers['Accept'] = 'application/json';
    request.files.add(await http.MultipartFile.fromPath('imagen', foto.path));
    final response = await http.Response.fromStream(await request.send());
    final decoded = jsonDecode(response.body);
    if (response.statusCode < 200 || response.statusCode >= 300) {
      final message = decoded is Map<String, dynamic>
          ? decoded['message'] ?? decoded['error']
          : null;
      throw Exception(message ?? 'Error del servidor: ${response.statusCode}');
    }
    return decoded as Map<String, dynamic>;
  }

  Future<void> _procesarFrame({bool silencioso = false}) async {
    final controller = _controller;
    if (controller == null ||
        !controller.value.isInitialized ||
        _enviando ||
        _consultando ||
        controller.value.isTakingPicture) {
      return;
    }

    setState(() => _enviando = true);
    try {
      final foto = await controller.takePicture();
      final respuesta = await _enviarFoto(
        foto,
        endpoint: '/api/v1/pasos/frame',
      );
      if (!mounted) return;
      setState(() {
        _ultimoRegistro = (respuesta['paso'] ?? respuesta['registro'])
            as Map<String, dynamic>?;
      });
    } catch (error) {
      if (mounted && !silencioso) _mostrarError(error);
    } finally {
      if (mounted) setState(() => _enviando = false);
    }
  }

  void _iniciarCaseta() {
    if (!_camaraLista || _casetaActiva) return;
    setState(() => _casetaActiva = true);
    _procesarFrame(silencioso: true);
    _timerCaseta = Timer.periodic(
      const Duration(milliseconds: 1800),
      (_) => _procesarFrame(silencioso: true),
    );
  }

  void _detenerCaseta() {
    _timerCaseta?.cancel();
    _timerCaseta = null;
    if (mounted) setState(() => _casetaActiva = false);
  }

  Future<void> _consultarTexto() async {
    if (_casetaActiva) _detenerCaseta();
    final texto = _placaController.text.trim();
    if (texto.isEmpty) {
      _mostrarError(Exception('Escribe una placa para realizar la consulta'));
      return;
    }
    setState(() {
      _consultando = true;
      _respuestaConsulta = null;
    });
    try {
      final response = await http.post(
        Uri.parse('${AppConfig.baseUrl}/api/v1/placas/consultar'),
        headers: {
          'Authorization': 'Bearer ${await _token()}',
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        body: jsonEncode({'texto': texto}),
      );
      final decoded = jsonDecode(response.body) as Map<String, dynamic>;
      if (response.statusCode < 200 || response.statusCode >= 300) {
        throw Exception(decoded['message'] ?? 'Error HTTP ${response.statusCode}');
      }
      if (mounted) setState(() => _respuestaConsulta = decoded);
    } catch (error) {
      if (mounted) _mostrarError(error);
    } finally {
      if (mounted) setState(() => _consultando = false);
    }
  }

  Future<void> _consultarConFoto() async {
    if (_casetaActiva) _detenerCaseta();
    final controller = _controller;
    if (controller == null || !controller.value.isInitialized || _enviando) {
      return;
    }
    setState(() {
      _consultando = true;
      _enviando = true;
      _respuestaConsulta = null;
    });
    try {
      final foto = await controller.takePicture();
      final respuesta = await _enviarFoto(
        foto,
        endpoint: '/api/v1/placas/consultar-imagen',
      );
      if (mounted) setState(() => _respuestaConsulta = respuesta);
    } catch (error) {
      if (mounted) _mostrarError(error);
    } finally {
      if (mounted) {
        setState(() {
          _consultando = false;
          _enviando = false;
        });
      }
    }
  }

  void _mostrarError(Object error) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(error.toString().replaceFirst('Exception: ', ''))),
    );
  }

  Future<void> _cerrarSesion() async {
    _detenerCaseta();
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
    _timerCaseta?.cancel();
    _placaController.dispose();
    _controller?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      child: Scaffold(
        backgroundColor: const Color(0xFF0F172A),
        appBar: AppBar(
          backgroundColor: const Color(0xFF1E293B),
          foregroundColor: Colors.white,
          title: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('Peaje Inteligente', style: TextStyle(fontSize: 18)),
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
          bottom: TabBar(
            onTap: (index) {
              if (index == 1 && _casetaActiva) _detenerCaseta();
            },
            tabs: [
              Tab(icon: Icon(Icons.local_taxi), text: 'Modo Caseta'),
              Tab(icon: Icon(Icons.search), text: 'Modo Consulta'),
            ],
          ),
        ),
        body: TabBarView(
          children: [
            _construirModoCaseta(),
            _construirModoConsulta(),
          ],
        ),
      ),
    );
  }

  Widget _construirVistaCamara() {
    if (_errorCamara != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Text(_errorCamara!, textAlign: TextAlign.center),
        ),
      );
    }
    if (!_camaraLista || _controller == null) {
      return const Center(child: CircularProgressIndicator());
    }
    return ClipRRect(
      borderRadius: BorderRadius.circular(16),
      child: CameraPreview(_controller!),
    );
  }

  Widget _construirModoCaseta() {
    final registro = _ultimoRegistro;
    return SafeArea(
      child: Column(
        children: [
          Expanded(
            child: Container(
              width: double.infinity,
              margin: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.black,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: Colors.blueGrey.shade700),
              ),
              clipBehavior: Clip.antiAlias,
              child: _construirVistaCamara(),
            ),
          ),
          if (registro != null) _tarjetaResultado(registro),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 20),
            child: SizedBox(
              width: double.infinity,
              height: 54,
              child: ElevatedButton.icon(
                onPressed: !_camaraLista
                    ? null
                    : (_casetaActiva ? _detenerCaseta : _iniciarCaseta),
                icon: Icon(_casetaActiva ? Icons.stop : Icons.play_arrow),
                label: Text(
                  _casetaActiva
                      ? 'Detener Caseta'
                      : 'Iniciar Caseta · captura cada 1.8 s',
                ),
                style: ElevatedButton.styleFrom(
                  backgroundColor:
                      _casetaActiva ? Colors.red.shade700 : Colors.green.shade700,
                  foregroundColor: Colors.white,
                ),
              ),
            ),
          ),
          if (_enviando)
            const Padding(
              padding: EdgeInsets.only(bottom: 12),
              child: Text('Enviando y procesando frame…'),
            ),
        ],
      ),
    );
  }

  Widget _tarjetaResultado(Map<String, dynamic> registro) {
    final tarifa = registro['tarifa'];
    final monto = tarifa is Map<String, dynamic>
        ? tarifa['monto'] ?? '0.00'
        : tarifa ?? '0.00';
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFF1E293B),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: Colors.blueAccent.withValues(alpha: 0.5)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Placa: ${registro['placa_detectada'] ?? registro['placa'] ?? 'No legible'}',
            style: const TextStyle(
              color: Colors.amber,
              fontWeight: FontWeight.bold,
              fontSize: 18,
            ),
          ),
          const SizedBox(height: 6),
          Wrap(
            spacing: 12,
            runSpacing: 6,
            children: [
              _dato('Categoría', registro['clase_vehiculo'] ?? registro['categoria']),
              _dato('Ejes', registro['ejes']),
              _dato('Pasajeros', registro['pasajeros']),
              _dato('Tarifa', 'Bs. $monto'),
              _dato('Estado', registro['estado']),
            ],
          ),
        ],
      ),
    );
  }

  Widget _dato(String titulo, Object? valor) => Text(
        '$titulo: ${valor ?? '—'}',
        style: const TextStyle(color: Colors.white70, fontSize: 13),
      );

  Widget _construirModoConsulta() {
    final resultado = _respuestaConsulta;
    return SafeArea(
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          TextField(
            controller: _placaController,
            textCapitalization: TextCapitalization.characters,
            decoration: InputDecoration(
              labelText: 'Placa del vehículo',
              hintText: 'Ej. 9001QAA',
              prefixIcon: const Icon(Icons.directions_car),
              filled: true,
              fillColor: const Color(0xFF1E293B),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
              ),
            ),
            onSubmitted: (_) => _consultarTexto(),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: _consultando ? null : _consultarTexto,
                  icon: const Icon(Icons.search),
                  label: const Text('Consultar texto'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: !_camaraLista || _consultando
                      ? null
                      : _consultarConFoto,
                  icon: const Icon(Icons.camera_alt),
                  label: const Text('Tomar foto'),
                ),
              ),
            ],
          ),
          if (_consultando)
            const Padding(
              padding: EdgeInsets.all(20),
              child: Center(child: CircularProgressIndicator()),
            ),
          if (resultado != null) ...[
            const SizedBox(height: 20),
            Card(
              color: const Color(0xFF1E293B),
              child: Padding(
                padding: const EdgeInsets.all(18),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      resultado['estado']?.toString() ?? 'Consulta',
                      style: const TextStyle(
                        color: Colors.amber,
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 10),
                    Text(
                      resultado['mensaje']?.toString() ?? 'Sin detalle',
                      style: const TextStyle(color: Colors.white70),
                    ),
                    if (resultado['placa'] != null) ...[
                      const SizedBox(height: 8),
                      Text('Placa reconocida: ${resultado['placa']}'),
                    ],
                    if (resultado['sugerencias'] is List &&
                        (resultado['sugerencias'] as List).isNotEmpty) ...[
                      const SizedBox(height: 8),
                      Text(
                        'Sugerencias OCR: ${(resultado['sugerencias'] as List).join(', ')}',
                        style: const TextStyle(color: Colors.lightBlueAccent),
                      ),
                    ],
                  ],
                ),
              ),
            ),
          ],
          const SizedBox(height: 18),
          Text(
            'Estados: Registrada y con paso · Registrada sin paso · Identificada no registrada · No existe',
            style: TextStyle(color: Colors.blueGrey.shade200, fontSize: 12),
          ),
          const SizedBox(height: 16),
          SizedBox(height: 240, child: _construirVistaCamara()),
        ],
      ),
    );
  }
}

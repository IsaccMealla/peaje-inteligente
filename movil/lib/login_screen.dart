import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:google_sign_in/google_sign_in.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'config.dart';
import 'camara_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  bool _cargando = false;
  String? _mensajeError;

  @override
  void initState() {
    super.initState();
    _inicializarGoogle();
  }

  Future<void> _inicializarGoogle() async {
    try {
      await GoogleSignIn.instance.initialize(
        serverClientId: AppConfig.googleWebClientId,
      );
    } catch (e) {
      if (mounted) {
        setState(() {
          _mensajeError = 'No se pudo inicializar Google: $e';
        });
      }
    }
  }

  Future<void> _iniciarSesionConGoogle() async {
    setState(() {
      _cargando = true;
      _mensajeError = null;
    });

    try {
      final GoogleSignInAccount cuenta =
          await GoogleSignIn.instance.authenticate();
      final String? idToken = cuenta.authentication.idToken;

      if (idToken == null) {
        throw Exception('Google no devolvió un idToken. Verifica el Web Client ID.');
      }

      final respuesta = await http.post(
        Uri.parse('${AppConfig.baseUrl}/api/v1/auth/google'),
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: jsonEncode({'id_token': idToken}),
      );

      final datos = jsonDecode(respuesta.body);

      if (respuesta.statusCode == 200 && datos['token'] != null) {
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString('auth_token', datos['token']);
        await prefs.setString('usuario_nombre', datos['usuario']['nombre'] ?? 'Operador');

        if (!mounted) return;
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (_) => const CamaraScreen()),
        );
      } else {
        setState(() {
          _mensajeError = datos['error'] ?? 'Error de autenticación (${respuesta.statusCode})';
        });
      }
    } on GoogleSignInException catch (e) {
      setState(() {
        if (e.code == GoogleSignInExceptionCode.canceled) {
          _mensajeError = 'Inicio de sesión cancelado.';
        } else if (e.code == GoogleSignInExceptionCode.unknownError &&
            (e.description ?? '').contains('28444')) {
          _mensajeError =
              'Google no reconoce esta app Android. '
              'Verifica el tipo Android en Google Cloud: '
              'paquete com.miempresa.movil y la huella SHA-1 '
              '9F:C4:63:4A:9E:99:52:AA:BB:3C:61:81:1A:C6:AE:58:34:A0:37:0D.';
        } else {
          _mensajeError =
              'Error de configuración de Google (${e.code}). ${e.description ?? ''}';
        }
      });
    } catch (e) {
      setState(() {
        _mensajeError = 'Error al iniciar sesión: $e';
      });
    } finally {
      if (mounted) {
        setState(() => _cargando = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF0F172A),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(24.0),
          child: Card(
            color: const Color(0xFF1E293B),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
            child: Padding(
              padding: const EdgeInsets.all(28.0),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.toll, size: 64, color: Colors.blueAccent),
                  const SizedBox(height: 16),
                  const Text(
                    'Peaje Inteligente',
                    style: TextStyle(
                      fontSize: 24,
                      fontWeight: FontWeight.bold,
                      color: Colors.white,
                    ),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Control vehicular en caseta',
                    style: TextStyle(color: Colors.white70, fontSize: 14),
                  ),
                  const SizedBox(height: 28),
                  if (_mensajeError != null) ...[
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: Colors.red.withValues(alpha: 0.15),
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(color: Colors.redAccent),
                      ),
                      child: Text(
                        _mensajeError!,
                        style: const TextStyle(color: Colors.redAccent, fontSize: 13),
                        textAlign: TextAlign.center,
                      ),
                    ),
                    const SizedBox(height: 20),
                  ],
                  SizedBox(
                    width: double.infinity,
                    height: 48,
                    child: ElevatedButton.icon(
                      onPressed: _cargando ? null : _iniciarSesionConGoogle,
                      icon: _cargando
                          ? const SizedBox(
                              width: 20,
                              height: 20,
                              child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                            )
                          : const Icon(Icons.login),
                      label: Text(_cargando ? 'Verificando...' : 'Iniciar sesión con Google'),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.blueAccent,
                        foregroundColor: Colors.white,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
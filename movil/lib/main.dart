import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'camara_screen.dart';
import 'login_screen.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final prefs = await SharedPreferences.getInstance();
  final tieneToken = prefs.getString('auth_token') != null;

  runApp(PeajeMovilApp(autenticado: tieneToken));
}

class PeajeMovilApp extends StatelessWidget {
  final bool autenticado;

  const PeajeMovilApp({super.key, required this.autenticado});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Peaje Inteligente',
      debugShowCheckedModeBanner: false,
      theme: ThemeData.dark(useMaterial3: true),
      home: autenticado ? const CamaraScreen() : const LoginScreen(),
    );
  }
}

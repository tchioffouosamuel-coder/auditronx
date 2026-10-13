import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

class AuthPatternBackground extends StatelessWidget {
  const AuthPatternBackground({super.key});

  static const assetPath = 'assets/patterns/auth-pattern.svg';
  static const tileSize = 240.0;

  @override
  Widget build(BuildContext context) => IgnorePointer(
    child: ExcludeSemantics(
      child: RepaintBoundary(
        child: LayoutBuilder(
          builder: (context, constraints) => Stack(
            children: [
              for (
                var row = 0;
                row < (constraints.maxHeight / tileSize).ceil();
                row++
              )
                for (
                  var column = 0;
                  column < (constraints.maxWidth / tileSize).ceil();
                  column++
                )
                  Positioned(
                    left: column * tileSize,
                    top: row * tileSize,
                    width: tileSize,
                    height: tileSize,
                    child: SvgPicture.asset(
                      assetPath,
                      excludeFromSemantics: true,
                    ),
                  ),
            ],
          ),
        ),
      ),
    ),
  );
}

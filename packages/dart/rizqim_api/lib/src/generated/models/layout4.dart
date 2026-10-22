// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'layout4.g.dart';

@JsonSerializable()
class Layout4 {
  const Layout4({
    required this.x,
    required this.y,
    required this.w,
    required this.h,
  });

  factory Layout4.fromJson(Map<String, Object?> json) =>
      _$Layout4FromJson(json);

  final String? x;
  final String? y;
  final String? w;
  final String? h;

  Map<String, Object?> toJson() => _$Layout4ToJson(this);
}

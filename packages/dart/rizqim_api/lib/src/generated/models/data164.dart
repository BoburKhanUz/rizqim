// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'settings3.dart';
import 'options6.dart';

part 'data164.g.dart';

@JsonSerializable()
class Data164 {
  const Data164({required this.settings, required this.options});

  factory Data164.fromJson(Map<String, Object?> json) =>
      _$Data164FromJson(json);

  final Settings3 settings;
  final Options6 options;

  Map<String, Object?> toJson() => _$Data164ToJson(this);
}

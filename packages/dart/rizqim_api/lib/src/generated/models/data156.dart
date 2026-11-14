// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'settings2.dart';
import 'merchants2.dart';
import 'options5.dart';

part 'data156.g.dart';

@JsonSerializable()
class Data156 {
  const Data156({
    required this.settings,
    required this.merchants,
    required this.options,
  });

  factory Data156.fromJson(Map<String, Object?> json) =>
      _$Data156FromJson(json);

  final Settings2 settings;
  final List<Merchants2> merchants;
  final Options5 options;

  Map<String, Object?> toJson() => _$Data156ToJson(this);
}

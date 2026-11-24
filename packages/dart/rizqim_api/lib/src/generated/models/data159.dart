// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'settings2.dart';
import 'merchants2.dart';
import 'options5.dart';

part 'data159.g.dart';

@JsonSerializable()
class Data159 {
  const Data159({
    required this.settings,
    required this.merchants,
    required this.options,
  });

  factory Data159.fromJson(Map<String, Object?> json) =>
      _$Data159FromJson(json);

  final Settings2 settings;
  final List<Merchants2> merchants;
  final Options5 options;

  Map<String, Object?> toJson() => _$Data159ToJson(this);
}

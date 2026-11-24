// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'settings.dart';
import 'merchants.dart';
import 'options4.dart';

part 'data158.g.dart';

@JsonSerializable()
class Data158 {
  const Data158({
    required this.settings,
    required this.merchants,
    required this.options,
  });

  factory Data158.fromJson(Map<String, Object?> json) =>
      _$Data158FromJson(json);

  final Settings settings;
  final List<Merchants> merchants;
  final Options4 options;

  Map<String, Object?> toJson() => _$Data158ToJson(this);
}

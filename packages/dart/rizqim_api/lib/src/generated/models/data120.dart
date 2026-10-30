// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'settings.dart';
import 'options.dart';

part 'data120.g.dart';

@JsonSerializable()
class Data120 {
  const Data120({required this.settings, required this.options});

  factory Data120.fromJson(Map<String, Object?> json) =>
      _$Data120FromJson(json);

  final Settings settings;
  final Options options;

  Map<String, Object?> toJson() => _$Data120ToJson(this);
}

// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'settings.dart';
import 'options4.dart';

part 'data136.g.dart';

@JsonSerializable()
class Data136 {
  const Data136({required this.settings, required this.options});

  factory Data136.fromJson(Map<String, Object?> json) =>
      _$Data136FromJson(json);

  final Settings settings;
  final Options4 options;

  Map<String, Object?> toJson() => _$Data136ToJson(this);
}

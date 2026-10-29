// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'settings2.dart';
import 'options2.dart';

part 'data121.g.dart';

@JsonSerializable()
class Data121 {
  const Data121({required this.settings, required this.options});

  factory Data121.fromJson(Map<String, Object?> json) =>
      _$Data121FromJson(json);

  final Settings2 settings;
  final Options2 options;

  Map<String, Object?> toJson() => _$Data121ToJson(this);
}

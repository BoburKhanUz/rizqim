// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'settings3.dart';
import 'options6.dart';

part 'data141.g.dart';

@JsonSerializable()
class Data141 {
  const Data141({required this.settings, required this.options});

  factory Data141.fromJson(Map<String, Object?> json) =>
      _$Data141FromJson(json);

  final Settings3 settings;
  final Options6 options;

  Map<String, Object?> toJson() => _$Data141ToJson(this);
}

// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'settings5.dart';

part 'data188.g.dart';

@JsonSerializable()
class Data188 {
  const Data188({required this.channels, required this.settings});

  factory Data188.fromJson(Map<String, Object?> json) =>
      _$Data188FromJson(json);

  final List<dynamic> channels;
  final List<Settings5> settings;

  Map<String, Object?> toJson() => _$Data188ToJson(this);
}

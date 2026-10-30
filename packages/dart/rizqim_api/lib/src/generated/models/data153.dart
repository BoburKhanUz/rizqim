// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'settings3.dart';

part 'data153.g.dart';

@JsonSerializable()
class Data153 {
  const Data153({required this.channels, required this.settings});

  factory Data153.fromJson(Map<String, Object?> json) =>
      _$Data153FromJson(json);

  final List<dynamic> channels;
  final List<Settings3> settings;

  Map<String, Object?> toJson() => _$Data153ToJson(this);
}

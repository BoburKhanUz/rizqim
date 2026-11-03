// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'settings3.dart';

part 'data154.g.dart';

@JsonSerializable()
class Data154 {
  const Data154({required this.channels, required this.settings});

  factory Data154.fromJson(Map<String, Object?> json) =>
      _$Data154FromJson(json);

  final List<dynamic> channels;
  final List<Settings3> settings;

  Map<String, Object?> toJson() => _$Data154ToJson(this);
}

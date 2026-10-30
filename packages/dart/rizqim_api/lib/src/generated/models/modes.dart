// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'modes.g.dart';

@JsonSerializable()
class Modes {
  const Modes({required this.key, required this.label});

  factory Modes.fromJson(Map<String, Object?> json) => _$ModesFromJson(json);

  final String key;
  final String label;

  Map<String, Object?> toJson() => _$ModesToJson(this);
}

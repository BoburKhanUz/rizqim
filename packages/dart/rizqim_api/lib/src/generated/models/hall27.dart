// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'hall27.g.dart';

@JsonSerializable()
class Hall27 {
  const Hall27({required this.id, required this.name});

  factory Hall27.fromJson(Map<String, Object?> json) => _$Hall27FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$Hall27ToJson(this);
}

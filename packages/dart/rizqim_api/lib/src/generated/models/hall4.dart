// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'hall4.g.dart';

@JsonSerializable()
class Hall4 {
  const Hall4({required this.id, required this.name});

  factory Hall4.fromJson(Map<String, Object?> json) => _$Hall4FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$Hall4ToJson(this);
}

// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'branches4.g.dart';

@JsonSerializable()
class Branches4 {
  const Branches4({required this.id, required this.name});

  factory Branches4.fromJson(Map<String, Object?> json) =>
      _$Branches4FromJson(json);

  final int id;
  final String name;

  Map<String, Object?> toJson() => _$Branches4ToJson(this);
}

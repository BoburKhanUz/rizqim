// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'employee4.g.dart';

@JsonSerializable()
class Employee4 {
  const Employee4({required this.id, required this.name});

  factory Employee4.fromJson(Map<String, Object?> json) =>
      _$Employee4FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$Employee4ToJson(this);
}

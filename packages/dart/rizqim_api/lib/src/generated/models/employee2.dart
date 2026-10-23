// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'employee2.g.dart';

@JsonSerializable()
class Employee2 {
  const Employee2({required this.id, required this.name});

  factory Employee2.fromJson(Map<String, Object?> json) =>
      _$Employee2FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$Employee2ToJson(this);
}

// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'employee5.g.dart';

@JsonSerializable()
class Employee5 {
  const Employee5({required this.id, required this.name});

  factory Employee5.fromJson(Map<String, Object?> json) =>
      _$Employee5FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$Employee5ToJson(this);
}

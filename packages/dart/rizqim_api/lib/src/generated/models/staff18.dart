// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'staff18.g.dart';

@JsonSerializable()
class Staff18 {
  const Staff18({required this.id, required this.name});

  factory Staff18.fromJson(Map<String, Object?> json) =>
      _$Staff18FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$Staff18ToJson(this);
}

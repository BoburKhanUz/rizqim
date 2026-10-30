// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'staff15.g.dart';

@JsonSerializable()
class Staff15 {
  const Staff15({required this.id, required this.name});

  factory Staff15.fromJson(Map<String, Object?> json) =>
      _$Staff15FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$Staff15ToJson(this);
}

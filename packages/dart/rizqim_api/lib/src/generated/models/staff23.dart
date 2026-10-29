// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'staff23.g.dart';

@JsonSerializable()
class Staff23 {
  const Staff23({required this.id, required this.name});

  factory Staff23.fromJson(Map<String, Object?> json) =>
      _$Staff23FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$Staff23ToJson(this);
}

// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'staff22.g.dart';

@JsonSerializable()
class Staff22 {
  const Staff22({required this.id, required this.name});

  factory Staff22.fromJson(Map<String, Object?> json) =>
      _$Staff22FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$Staff22ToJson(this);
}

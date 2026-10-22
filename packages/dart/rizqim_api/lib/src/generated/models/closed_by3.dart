// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'closed_by3.g.dart';

@JsonSerializable()
class ClosedBy3 {
  const ClosedBy3({required this.id, required this.name});

  factory ClosedBy3.fromJson(Map<String, Object?> json) =>
      _$ClosedBy3FromJson(json);

  final int id;
  final String name;

  Map<String, Object?> toJson() => _$ClosedBy3ToJson(this);
}

// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'closed_by5.g.dart';

@JsonSerializable()
class ClosedBy5 {
  const ClosedBy5({required this.id, required this.name});

  factory ClosedBy5.fromJson(Map<String, Object?> json) =>
      _$ClosedBy5FromJson(json);

  final int id;
  final String name;

  Map<String, Object?> toJson() => _$ClosedBy5ToJson(this);
}

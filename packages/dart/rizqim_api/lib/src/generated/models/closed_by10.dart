// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'closed_by10.g.dart';

@JsonSerializable()
class ClosedBy10 {
  const ClosedBy10({required this.id, required this.name});

  factory ClosedBy10.fromJson(Map<String, Object?> json) =>
      _$ClosedBy10FromJson(json);

  final int id;
  final String name;

  Map<String, Object?> toJson() => _$ClosedBy10ToJson(this);
}

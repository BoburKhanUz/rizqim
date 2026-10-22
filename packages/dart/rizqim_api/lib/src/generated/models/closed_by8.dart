// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'closed_by8.g.dart';

@JsonSerializable()
class ClosedBy8 {
  const ClosedBy8({required this.id, required this.name});

  factory ClosedBy8.fromJson(Map<String, Object?> json) =>
      _$ClosedBy8FromJson(json);

  final int id;
  final String name;

  Map<String, Object?> toJson() => _$ClosedBy8ToJson(this);
}

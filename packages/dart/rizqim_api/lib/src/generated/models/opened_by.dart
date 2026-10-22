// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'opened_by.g.dart';

@JsonSerializable()
class OpenedBy {
  const OpenedBy({required this.id, required this.name});

  factory OpenedBy.fromJson(Map<String, Object?> json) =>
      _$OpenedByFromJson(json);

  final int id;
  final String name;

  Map<String, Object?> toJson() => _$OpenedByToJson(this);
}

// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'resolved_by.g.dart';

@JsonSerializable()
class ResolvedBy {
  const ResolvedBy({required this.id, required this.name});

  factory ResolvedBy.fromJson(Map<String, Object?> json) =>
      _$ResolvedByFromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$ResolvedByToJson(this);
}

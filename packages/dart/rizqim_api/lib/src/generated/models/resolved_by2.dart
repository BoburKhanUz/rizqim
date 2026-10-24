// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'resolved_by2.g.dart';

@JsonSerializable()
class ResolvedBy2 {
  const ResolvedBy2({required this.id, required this.name});

  factory ResolvedBy2.fromJson(Map<String, Object?> json) =>
      _$ResolvedBy2FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$ResolvedBy2ToJson(this);
}

// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'resolved_by3.g.dart';

@JsonSerializable()
class ResolvedBy3 {
  const ResolvedBy3({required this.id, required this.name});

  factory ResolvedBy3.fromJson(Map<String, Object?> json) =>
      _$ResolvedBy3FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$ResolvedBy3ToJson(this);
}

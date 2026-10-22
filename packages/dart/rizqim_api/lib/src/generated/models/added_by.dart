// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'added_by.g.dart';

@JsonSerializable()
class AddedBy {
  const AddedBy({required this.id, required this.name});

  factory AddedBy.fromJson(Map<String, Object?> json) =>
      _$AddedByFromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$AddedByToJson(this);
}

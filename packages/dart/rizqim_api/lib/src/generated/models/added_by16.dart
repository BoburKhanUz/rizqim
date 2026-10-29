// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'added_by16.g.dart';

@JsonSerializable()
class AddedBy16 {
  const AddedBy16({required this.id, required this.name});

  factory AddedBy16.fromJson(Map<String, Object?> json) =>
      _$AddedBy16FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$AddedBy16ToJson(this);
}

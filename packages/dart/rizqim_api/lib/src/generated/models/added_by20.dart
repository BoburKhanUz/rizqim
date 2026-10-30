// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'added_by20.g.dart';

@JsonSerializable()
class AddedBy20 {
  const AddedBy20({required this.id, required this.name});

  factory AddedBy20.fromJson(Map<String, Object?> json) =>
      _$AddedBy20FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$AddedBy20ToJson(this);
}

// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'added_by3.g.dart';

@JsonSerializable()
class AddedBy3 {
  const AddedBy3({required this.id, required this.name});

  factory AddedBy3.fromJson(Map<String, Object?> json) =>
      _$AddedBy3FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$AddedBy3ToJson(this);
}

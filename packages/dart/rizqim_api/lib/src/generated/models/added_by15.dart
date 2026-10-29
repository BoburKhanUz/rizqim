// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'added_by15.g.dart';

@JsonSerializable()
class AddedBy15 {
  const AddedBy15({required this.id, required this.name});

  factory AddedBy15.fromJson(Map<String, Object?> json) =>
      _$AddedBy15FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$AddedBy15ToJson(this);
}

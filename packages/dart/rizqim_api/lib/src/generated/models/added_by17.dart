// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'added_by17.g.dart';

@JsonSerializable()
class AddedBy17 {
  const AddedBy17({required this.id, required this.name});

  factory AddedBy17.fromJson(Map<String, Object?> json) =>
      _$AddedBy17FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$AddedBy17ToJson(this);
}

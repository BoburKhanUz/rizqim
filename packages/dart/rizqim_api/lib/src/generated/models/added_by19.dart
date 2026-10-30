// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'added_by19.g.dart';

@JsonSerializable()
class AddedBy19 {
  const AddedBy19({required this.id, required this.name});

  factory AddedBy19.fromJson(Map<String, Object?> json) =>
      _$AddedBy19FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$AddedBy19ToJson(this);
}

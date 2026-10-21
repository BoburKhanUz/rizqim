// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'added_by9.g.dart';

@JsonSerializable()
class AddedBy9 {
  const AddedBy9({required this.id, required this.name});

  factory AddedBy9.fromJson(Map<String, Object?> json) =>
      _$AddedBy9FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$AddedBy9ToJson(this);
}

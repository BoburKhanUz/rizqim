// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'branch22.g.dart';

@JsonSerializable()
class Branch22 {
  const Branch22({required this.id, required this.name});

  factory Branch22.fromJson(Map<String, Object?> json) =>
      _$Branch22FromJson(json);

  final int id;
  final String name;

  Map<String, Object?> toJson() => _$Branch22ToJson(this);
}

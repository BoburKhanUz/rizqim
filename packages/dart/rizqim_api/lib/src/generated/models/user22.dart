// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'user22.g.dart';

@JsonSerializable()
class User22 {
  const User22({required this.id, required this.name});

  factory User22.fromJson(Map<String, Object?> json) => _$User22FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$User22ToJson(this);
}

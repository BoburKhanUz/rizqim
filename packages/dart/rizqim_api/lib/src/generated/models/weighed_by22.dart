// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'weighed_by22.g.dart';

@JsonSerializable()
class WeighedBy22 {
  const WeighedBy22({required this.id, required this.name});

  factory WeighedBy22.fromJson(Map<String, Object?> json) =>
      _$WeighedBy22FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$WeighedBy22ToJson(this);
}

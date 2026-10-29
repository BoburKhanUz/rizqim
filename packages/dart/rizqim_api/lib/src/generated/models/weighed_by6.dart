// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'weighed_by6.g.dart';

@JsonSerializable()
class WeighedBy6 {
  const WeighedBy6({required this.id, required this.name});

  factory WeighedBy6.fromJson(Map<String, Object?> json) =>
      _$WeighedBy6FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$WeighedBy6ToJson(this);
}

// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'weighed_by25.g.dart';

@JsonSerializable()
class WeighedBy25 {
  const WeighedBy25({required this.id, required this.name});

  factory WeighedBy25.fromJson(Map<String, Object?> json) =>
      _$WeighedBy25FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$WeighedBy25ToJson(this);
}

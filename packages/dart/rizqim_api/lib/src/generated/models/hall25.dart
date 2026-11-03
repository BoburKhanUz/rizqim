// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'hall25.g.dart';

@JsonSerializable()
class Hall25 {
  const Hall25({required this.id, required this.name});

  factory Hall25.fromJson(Map<String, Object?> json) => _$Hall25FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$Hall25ToJson(this);
}

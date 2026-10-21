// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'hall8.g.dart';

@JsonSerializable()
class Hall8 {
  const Hall8({required this.id, required this.name});

  factory Hall8.fromJson(Map<String, Object?> json) => _$Hall8FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$Hall8ToJson(this);
}

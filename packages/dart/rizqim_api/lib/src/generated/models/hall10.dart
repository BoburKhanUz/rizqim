// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'hall10.g.dart';

@JsonSerializable()
class Hall10 {
  const Hall10({required this.id, required this.name});

  factory Hall10.fromJson(Map<String, Object?> json) => _$Hall10FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$Hall10ToJson(this);
}

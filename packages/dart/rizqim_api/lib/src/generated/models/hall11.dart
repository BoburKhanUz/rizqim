// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'hall11.g.dart';

@JsonSerializable()
class Hall11 {
  const Hall11({required this.id, required this.name});

  factory Hall11.fromJson(Map<String, Object?> json) => _$Hall11FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$Hall11ToJson(this);
}

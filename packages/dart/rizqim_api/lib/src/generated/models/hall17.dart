// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'hall17.g.dart';

@JsonSerializable()
class Hall17 {
  const Hall17({required this.id, required this.name});

  factory Hall17.fromJson(Map<String, Object?> json) => _$Hall17FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$Hall17ToJson(this);
}

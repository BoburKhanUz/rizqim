// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'hall28.g.dart';

@JsonSerializable()
class Hall28 {
  const Hall28({required this.id, required this.name});

  factory Hall28.fromJson(Map<String, Object?> json) => _$Hall28FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$Hall28ToJson(this);
}

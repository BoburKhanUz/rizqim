// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data36.g.dart';

@JsonSerializable()
class Data36 {
  const Data36({required this.features});

  factory Data36.fromJson(Map<String, Object?> json) => _$Data36FromJson(json);

  final Map<String, bool> features;

  Map<String, Object?> toJson() => _$Data36ToJson(this);
}

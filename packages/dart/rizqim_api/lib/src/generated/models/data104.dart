// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data104.g.dart';

@JsonSerializable()
class Data104 {
  const Data104({required this.id});

  factory Data104.fromJson(Map<String, Object?> json) =>
      _$Data104FromJson(json);

  final int id;

  Map<String, Object?> toJson() => _$Data104ToJson(this);
}

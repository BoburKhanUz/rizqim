// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data103.g.dart';

@JsonSerializable()
class Data103 {
  const Data103({required this.id});

  factory Data103.fromJson(Map<String, Object?> json) =>
      _$Data103FromJson(json);

  final int id;

  Map<String, Object?> toJson() => _$Data103ToJson(this);
}

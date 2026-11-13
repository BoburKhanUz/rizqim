// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data293.g.dart';

@JsonSerializable()
class Data293 {
  const Data293({required this.received});

  factory Data293.fromJson(Map<String, Object?> json) =>
      _$Data293FromJson(json);

  final bool received;

  Map<String, Object?> toJson() => _$Data293ToJson(this);
}

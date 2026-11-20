// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data370.g.dart';

@JsonSerializable()
class Data370 {
  const Data370({required this.received});

  factory Data370.fromJson(Map<String, Object?> json) =>
      _$Data370FromJson(json);

  final bool received;

  Map<String, Object?> toJson() => _$Data370ToJson(this);
}

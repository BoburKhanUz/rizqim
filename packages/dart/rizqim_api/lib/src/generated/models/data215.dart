// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data215.g.dart';

@JsonSerializable()
class Data215 {
  const Data215({required this.received});

  factory Data215.fromJson(Map<String, Object?> json) =>
      _$Data215FromJson(json);

  final bool received;

  Map<String, Object?> toJson() => _$Data215ToJson(this);
}

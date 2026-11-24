// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data145.g.dart';

@JsonSerializable()
class Data145 {
  const Data145({required this.deleted});

  factory Data145.fromJson(Map<String, Object?> json) =>
      _$Data145FromJson(json);

  final bool deleted;

  Map<String, Object?> toJson() => _$Data145ToJson(this);
}

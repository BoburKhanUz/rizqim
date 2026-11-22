// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'event13.dart';

part 'data248.g.dart';

@JsonSerializable()
class Data248 {
  const Data248({required this.event});

  factory Data248.fromJson(Map<String, Object?> json) =>
      _$Data248FromJson(json);

  final Event13 event;

  Map<String, Object?> toJson() => _$Data248ToJson(this);
}

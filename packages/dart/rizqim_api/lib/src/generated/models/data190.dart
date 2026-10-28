// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data190.g.dart';

@JsonSerializable()
class Data190 {
  const Data190({required this.received});

  factory Data190.fromJson(Map<String, Object?> json) =>
      _$Data190FromJson(json);

  final bool received;

  Map<String, Object?> toJson() => _$Data190ToJson(this);
}

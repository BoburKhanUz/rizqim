// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data368.g.dart';

@JsonSerializable()
class Data368 {
  const Data368({required this.received});

  factory Data368.fromJson(Map<String, Object?> json) =>
      _$Data368FromJson(json);

  final bool received;

  Map<String, Object?> toJson() => _$Data368ToJson(this);
}

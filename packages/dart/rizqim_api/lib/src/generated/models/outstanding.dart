// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'outstanding.g.dart';

@JsonSerializable()
class Outstanding {
  const Outstanding({required this.count, required this.amount});

  factory Outstanding.fromJson(Map<String, Object?> json) =>
      _$OutstandingFromJson(json);

  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$OutstandingToJson(this);
}

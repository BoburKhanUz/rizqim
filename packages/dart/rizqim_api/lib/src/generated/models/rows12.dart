// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'rows12.g.dart';

@JsonSerializable()
class Rows12 {
  const Rows12({
    required this.uuid,
    required this.tender,
    required this.amount,
    required this.paidAt,
  });

  factory Rows12.fromJson(Map<String, Object?> json) => _$Rows12FromJson(json);

  final String uuid;
  final String tender;
  final String amount;
  @JsonKey(name: 'paid_at')
  final String? paidAt;

  Map<String, Object?> toJson() => _$Rows12ToJson(this);
}

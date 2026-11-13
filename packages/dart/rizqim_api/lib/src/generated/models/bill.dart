// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'bill.g.dart';

@JsonSerializable()
class Bill {
  const Bill({required this.uuid});

  factory Bill.fromJson(Map<String, Object?> json) => _$BillFromJson(json);

  final String uuid;

  Map<String, Object?> toJson() => _$BillToJson(this);
}

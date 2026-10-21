// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'applications.g.dart';

@JsonSerializable()
class Applications {
  const Applications({
    required this.billUuid,
    required this.amount,
    required this.at,
  });

  factory Applications.fromJson(Map<String, Object?> json) =>
      _$ApplicationsFromJson(json);

  @JsonKey(name: 'bill_uuid')
  final String billUuid;
  final String amount;
  final String at;

  Map<String, Object?> toJson() => _$ApplicationsToJson(this);
}

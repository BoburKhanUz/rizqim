// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'kassa_status2.g.dart';

@JsonSerializable()
class KassaStatus2 {
  const KassaStatus2({
    required this.registered,
    required this.kassaId,
    required this.message,
  });

  factory KassaStatus2.fromJson(Map<String, Object?> json) =>
      _$KassaStatus2FromJson(json);

  final bool registered;
  @JsonKey(name: 'kassa_id')
  final String? kassaId;
  final String? message;

  Map<String, Object?> toJson() => _$KassaStatus2ToJson(this);
}

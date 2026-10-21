// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data52.dart';

part 'get_v1_owner_invoices_response.g.dart';

@JsonSerializable()
class GetV1OwnerInvoicesResponse {
  const GetV1OwnerInvoicesResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerInvoicesResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1OwnerInvoicesResponseFromJson(json);

  final bool success;
  final String message;
  final Data52 data;

  Map<String, Object?> toJson() => _$GetV1OwnerInvoicesResponseToJson(this);
}
